import { Pressable, StyleSheet, Text, View } from "react-native";
import { formatMoney, statusLabel } from "../lib/format";
import type { Invoice } from "../lib/types";

export function InvoiceRow({ invoice, onPress }: { invoice: Invoice; onPress: () => void }) {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={`${invoice.number}, ${invoice.customer.name}, ${statusLabel(invoice)}`}
      onPress={onPress}
      style={styles.row}
    >
      <View style={styles.main}>
        <Text style={styles.title} numberOfLines={1}>
          {invoice.customer.name}
        </Text>
        <Text style={[styles.status, invoice.overdue && styles.overdue]}>{statusLabel(invoice)}</Text>
      </View>
      <Text style={styles.amount}>{formatMoney(invoice.amount_cents, invoice.currency)}</Text>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    minHeight: 64,
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: "#d4d4d8",
  },
  main: { flex: 1 },
  title: { fontSize: 16, fontWeight: "600" },
  status: { marginTop: 2, fontSize: 14, color: "#52525b" },
  overdue: { color: "#b91c1c" },
  amount: { fontSize: 16, fontWeight: "600", fontVariant: ["tabular-nums"] },
});
