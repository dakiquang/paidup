import { fireEvent, render, screen } from "@testing-library/react-native";
import { router } from "expo-router";
import LoginScreen from "../app/login";
import { ApiError, login } from "../lib/api";
import { saveToken } from "../lib/session";

jest.mock("expo-router", () => ({ router: { replace: jest.fn() } }));
jest.mock("../lib/session", () => ({ saveToken: jest.fn() }));
jest.mock("../lib/api", () => ({ ...jest.requireActual("../lib/api"), login: jest.fn() }));

const mockLogin = login as jest.Mock;

async function submit() {
  await render(<LoginScreen />);
  await fireEvent.changeText(screen.getByLabelText("Email"), " owner@example.com ");
  await fireEvent.changeText(screen.getByLabelText("Password"), "secret");
  await fireEvent.press(screen.getByRole("button", { name: "Sign in" }));
}

beforeEach(() => jest.clearAllMocks());

it("stores the token and goes to the invoices", async () => {
  mockLogin.mockResolvedValue({ token: "abc" });

  await submit();

  expect(mockLogin).toHaveBeenCalledWith("owner@example.com", "secret");
  expect(saveToken).toHaveBeenCalledWith("abc");
  expect(router.replace).toHaveBeenCalledWith("/");
});

it.each([
  [new ApiError(422, "x"), "Those details don't match."],
  [new ApiError(429, "x"), "Too many attempts. Wait a minute and try again."],
  [new TypeError("offline"), "Can't reach PaidUp. Check your connection."],
])("explains a failed sign in", async (failure, message) => {
  mockLogin.mockRejectedValue(failure);

  await submit();

  expect(await screen.findByText(message)).toBeTruthy();
  expect(saveToken).not.toHaveBeenCalled();
});
